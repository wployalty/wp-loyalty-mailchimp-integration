import React from 'react';
import Button from "./Button";
import {CommonContext, UiLabelContext} from "../../Context";

const TitleActionContainer = ({ title, saveAction, saveDisabled = false, showSave = true, syncAction, syncDisabled = false, syncLoading = false, showSync = false }) => {
    const labels = React.useContext(UiLabelContext);
    const {appState} = React.useContext(CommonContext);

    return <div className={`flex items-center justify-between gap-2 w-full`}>
        {/*left div*/}
        <p className={`text-dark font-bold text-md tracking-[1.28px]`}>{title}</p>
        {/*right div*/}
        <div className={`flex   items-center gap-2.5`}>
            <Button
                icon={<i className={`wlr wlrf-back text-md font-medium color-important`}/>}
                outline={true}
                bgColor={"bg-white"}
                outlineStyle={"border-light-border"}
                textStyle={"text-light"}
                click={() => location.replace(appState.common.back_to_apps_url)}
            >
                {labels.common.back_to_loyalty}
            </Button>

            {showSync && (
                <Button
                    click={syncAction}
                    disabled={syncDisabled || syncLoading}
                    icon={<i className={`wlr wlrf-refresh text-md font-medium color-important ${syncLoading ? 'animate-spin' : ''}`} />}
                >
                    {labels.settings?.perform_sync || "Perform Sync"}
                </Button>
            )}

            {showSave && (
                <Button
                    icon={<i className={`wlr wlrf-save text-md font-medium color-important `}/>}
                    click={saveAction}
                    disabled={saveDisabled}
                >
                    {labels.common.save}
                </Button>
            )}
        </div>
    </div>
};

export default TitleActionContainer;