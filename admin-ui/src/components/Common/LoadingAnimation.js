import React from "react";

const LoadingAnimation = ({height = "wlmi-h-full"}) => {
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
        <div className={`wlmi-bg-white  wlmi-text-primary wlmi-space-y-4 wlmi-flex wlmi-flex-col wlmi-items-center wlmi-justify-center  wlmi-w-full ${height}`}>
            <i className="wlr wlrf-spinner wlmi-animate-spin  wlmi-text-2xl wlmi-text-primary  "/>
            <p className="wlmi-text-sm wlmi-font-medium">{text}</p>
        </div>
    );
};

export default LoadingAnimation;

