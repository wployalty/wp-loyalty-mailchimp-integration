import React from "react";
import {CommonContext} from "../../Context";

const LoadingAnimation = ({height = "h-full"}) => {
    const {commonState} = React.useContext(CommonContext);
    const [text, setText] = React.useState("Loading...");

    React.useEffect(() => {
        let getText = setTimeout(() => {
            setText("If loading takes a while, please refresh the screen...!")
        }, 3000)

        return () => {
            clearInterval(getText)
        }
    }, [])

    return (
        <div className={`bg-white  text-primary space-y-4 flex flex-col items-center justify-center  w-full ${height}`}>
            <i className="wlr wlrf-spinner animate-spin  text-2xl   "
               style={{
                   color: `${commonState.design.colors.theme.primary}`
               }}
            />
            <p className="text-sm font-medium"
               style={{
                   color: `${commonState.design.colors.theme.primary}`
               }}
            >{text}</p>
        </div>
    );
};

export default LoadingAnimation;

