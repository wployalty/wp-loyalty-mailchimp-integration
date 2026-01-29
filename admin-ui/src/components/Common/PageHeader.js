import React from 'react';
import {getHexColor} from "../../helpers/utilities";
import {CommonContext} from "../../Context";
import Icon from "./Icon";

const PageHeader = ({title, handleBackIcon}) => {
    const {commonState} = React.useContext(CommonContext);
    const {design} = commonState;

    return <div className={`flex h-12  items-center  justify-between w-full px-4 lg:px-3 py-2 lg:py-3`}
                style={{backgroundColor: `${design.colors.theme.primary}`}}
    >
        <div className={`flex gap-x-3 items-center`}>
            <Icon
                click={handleBackIcon}
                icon={"back"}

                color={`${getHexColor(design.colors.theme.text)}`}
            />
            <p className={`text-md lg:text-sm  text-${design.colors.theme.text}`}
               dangerouslySetInnerHTML={{__html: title}}
            />
        </div>
        <div className={`flex items-center justify-center h-8 w-8 rounded-md `}
            // style={{background: `${getBackgroundColor(design.colors.theme.primary)}`}}
        >
            <Icon icon={"close"}
                  fontSize={"2xl:text-3xl text-2xl"}
                  color={`${getHexColor(design.colors.theme.text)}`}
            />
        </div>
    </div>
};

export default PageHeader;